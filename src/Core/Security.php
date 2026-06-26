<?php
/**
 * Clase centralizada de seguridad para Ecommerce-Tinkuy.
 * Implementa las cuatro capas de seguridad del sistema de autenticación:
 *
 *   1. CSRF protection  — generarCSRF(), validarCSRF($token)
 *   2. Rate limiting    — estaRateLimited($ip, $usuario, $conn),
 *                         registrarIntento($ip, $usuario, $conn),
 *                         limpiarIntentosExitosos($ip, $usuario, $conn)
 *   3. RBAC             — requerirRol($rol_requerido),
 *                         requerirAdmin(), requerirVendedor()
 *   4. Session hardening — validarTimeoutSesion(), configurarSesionSegura()
 *
 * Constantes de configuración (rate limiting):
 *   MAX_INTENTOS_IP      — Bloqueo por IP tras X fallos consecutivos en la ventana
 *   MAX_INTENTOS_USUARIO — Bloqueo por nombre de usuario tras X fallos
 *   VENTANA_MINUTOS      — Ventana de tiempo en minutos para contar intentos
 *
 * Nota: Requiere que la tabla 'login_intentos' exista en BD
 *       (esquema en database/create_login_intentos.sql).
 */
class Security
{

    // --- Configuración de rate limiting ---
    const MAX_INTENTOS_IP = 5;   // Bloqueo por IP luego de X fallos
    const MAX_INTENTOS_USUARIO = 5;   // Bloqueo por usuario luego de X fallos
    const VENTANA_MINUTOS = 5;  // Ventana de tiempo para contar intentos

    // =========================================================
    // CSRF
    // =========================================================

    /**
     * Devuelve el token CSRF de la sesión actual y lo genera si aún no existe.
     * El token se almacena en $_SESSION['csrf_token'].
     *
     * @return string Token CSRF hexadecimal de 64 caracteres
     */
    public static function generarCSRF(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Valida que el token CSRF enviado en el formulario coincida con el de sesión.
     * Usa hash_equals() para comparación en tiempo constante (previene timing attacks).
     *
     * @param string $token Token recibido del campo oculto del formulario
     * @return bool true si el token es válido, false en caso contrario
     */
    public static function verificarCSRF(string $token): bool
    {
        return isset($_SESSION['csrf_token'])
            && is_string($token)
            && strlen($token) > 0
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Genera un nuevo token CSRF y lo almacena en sesión.
     * Debe llamarse inmediatamente después de un login exitoso para invalidar el token anterior.
     *
     * @return void
     */
    public static function rotarCSRF(): void
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // =========================================================
    // IP
    // =========================================================

    /**
     * Devuelve la IP real del cliente, validada con FILTER_VALIDATE_IP.
     * Devuelve '0.0.0.0' si la IP no puede validarse.
     *
     * @return string IP del cliente o '0.0.0.0' como fallback seguro
     */
    public static function obtenerIP(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    // =========================================================
    // RATE LIMITING / FUERZA BRUTA
    // =========================================================

    /**
     * Consulta la tabla login_intentos y cuenta los intentos fallidos (exitoso=0) de la IP
     * o del usuario dentro de la ventana de tiempo configurada.
     * El campo de búsqueda puede ser 'ip' (para filtrar por dirección IP) o cualquier
     * otro valor, que se interpreta como filtro por nombre de usuario.
     * La ventana de tiempo se calcula como DATE_SUB(NOW(), INTERVAL N MINUTE) directamente
     * en la consulta SQL para evitar discrepancias con el reloj del servidor PHP.
     * Devuelve 0 si la consulta falla (prepare retorna false) en lugar de lanzar excepción.
     *
     * @param string $campo          'ip' para filtrar por dirección IP; cualquier otro para filtrar por usuario
     * @param string $valor          Valor a buscar: la IP del cliente o el nombre de usuario
     * @param int    $ventanaMinutos Ventana de tiempo en minutos hacia atrás desde NOW()
     * @param mysqli $conn           Conexión activa a la base de datos
     * @return int Número de intentos fallidos en la ventana; 0 si la consulta falla o no hay resultados
     */
    private static function contarIntentos(string $campo, string $valor, int $ventanaMinutos, $conn): int
    {
        $sql = $campo === 'ip'
            ? "SELECT COUNT(*) as total FROM login_intentos WHERE ip = ? AND exitoso = 0 AND fecha_intento >= DATE_SUB(NOW(), INTERVAL {$ventanaMinutos} MINUTE)"
            : "SELECT COUNT(*) as total FROM login_intentos WHERE usuario = ? AND exitoso = 0 AND fecha_intento >= DATE_SUB(NOW(), INTERVAL {$ventanaMinutos} MINUTE)";

        $stmt = $conn->prepare($sql);
        if (!$stmt)
            return 0;
        $stmt->bind_param("s", $valor);
        $stmt->execute();

        $resultado = $stmt->get_result();
        $fila = $resultado->fetch_assoc();
        $stmt->close();

        return (int) ($fila['total'] ?? 0);
    }

    /**
     * Determina si una IP o nombre de usuario deben ser bloqueados por superar el
     * número máximo de intentos fallidos permitido en la ventana de tiempo configurada.
     * Evalúa dos límites independientes: por dirección IP (MAX_INTENTOS_IP) y por
     * nombre de usuario (MAX_INTENTOS_USUARIO); devuelve true si CUALQUIERA supera su límite.
     * Si el nombre de usuario está vacío, omite la verificación por usuario y solo evalúa la IP.
     * Debe llamarse antes de validar credenciales para detener ataques de fuerza bruta
     * incluso antes de consultar la tabla de usuarios en la base de datos.
     *
     * @param string $ip      Dirección IP del cliente (obtenida con Security::obtenerIP())
     * @param string $usuario Nombre de usuario ingresado en el formulario; puede estar vacío
     * @param mysqli $conn    Conexión activa a la base de datos
     * @return bool true si el acceso debe bloquearse, false si puede continuar el flujo de login
     */
    public static function estaRateLimited(string $ip, string $usuario, $conn): bool
    {
        if (self::contarIntentos('ip', $ip, self::VENTANA_MINUTOS, $conn) >= self::MAX_INTENTOS_IP) {
            return true;
        }
        if (
            !empty($usuario) &&
            self::contarIntentos('usuario', $usuario, self::VENTANA_MINUTOS, $conn) >= self::MAX_INTENTOS_USUARIO
        ) {
            return true;
        }
        return false;
    }

    /**
     * Registra un intento de login en la tabla login_intentos.
     *
     * @param string $ip      IP del cliente
     * @param string $usuario Nombre de usuario ingresado en el formulario
     * @param bool   $exitoso true si el login fue exitoso, false si falló
     * @param mysqli $conn    Conexión activa a la base de datos
     * @return void
     */
    public static function registrarIntento(string $ip, string $usuario, bool $exitoso, $conn): void
    {
        $stmt = $conn->prepare(
            "INSERT INTO login_intentos (ip, usuario, exitoso, fecha_intento) VALUES (?, ?, ?, NOW())"
        );
        if (!$stmt)
            return;
        $exitoso_int = $exitoso ? 1 : 0;
        $stmt->bind_param("ssi", $ip, $usuario, $exitoso_int);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Elimina todos los intentos fallidos (exitoso=0) registrados para la IP y el usuario indicados.
     * Debe llamarse inmediatamente después de un login exitoso para reiniciar ambos contadores
     * y garantizar que un usuario legítimo no quede bloqueado por sus propios intentos previos.
     * La consulta usa OR (usuario = ? OR ip = ?) para limpiar ambos registros en una sola pasada,
     * evitando que uno quede en BD mientras el otro se limpia.
     * Si el usuario está vacío, igualmente limpia los registros de la IP especificada.
     *
     * @param string $ip      Dirección IP del cliente (para limpiar el contador de bloqueo por IP)
     * @param string $usuario Nombre de usuario (para limpiar el contador de bloqueo por usuario)
     * @param mysqli $conn    Conexión activa a la base de datos
     * @return void
     */
    public static function resetearIntentos(string $ip, string $usuario, $conn): void
    {
        if (empty($usuario))
            return;
        $stmt = $conn->prepare("DELETE FROM login_intentos WHERE (usuario = ? OR ip = ?) AND exitoso = 0");
        if ($stmt) {
            $stmt->bind_param("ss", $usuario, $ip);
            $stmt->execute();
            $stmt->close();
        }
    }

    /**
     * Calcula cuántos intentos fallidos quedan antes de que el rate-limiting bloquee el acceso.
     * Evalúa independientemente el límite por IP (MAX_INTENTOS_IP) y el límite por nombre de
     * usuario (MAX_INTENTOS_USUARIO), y devuelve el MENOR de los dos para representar el
     * cuello de botella real al que llegará el usuario primero.
     * Si el nombre de usuario está vacío, solo se evalúa el límite por IP.
     * Usado en AuthController para mostrar advertencias progresivas en el formulario de login.
     *
     * @param string $ip      Dirección IP del cliente
     * @param string $usuario Nombre de usuario ingresado (puede estar vacío)
     * @param mysqli $conn    Conexión activa a la base de datos
     * @return int Intentos restantes antes del bloqueo (0 si ya está o debe ser bloqueado)
     */
    public static function intentosRestantes(string $ip, string $usuario, $conn): int
    {
        $restantes_ip = max(0, self::MAX_INTENTOS_IP - self::contarIntentos('ip', $ip, self::VENTANA_MINUTOS, $conn));

        if (!empty($usuario)) {
            $restantes_u = max(0, self::MAX_INTENTOS_USUARIO - self::contarIntentos('usuario', $usuario, self::VENTANA_MINUTOS, $conn));
            return min($restantes_ip, $restantes_u);
        }
        return $restantes_ip;
    }

    /**
     * Calcula los segundos que faltan para que el bloqueo de rate-limiting expire.
     * Usa TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(fecha_intento, INTERVAL N MINUTE)) para
     * calcular cuándo expirará el intento que activó el bloqueo.
     * El OFFSET = num_rows - MAX_INTENTOS localiza exactamente qué intento inició el bloqueo
     * (no el más reciente, sino el N-ésimo desde el inicio de la ventana activa).
     * Evalúa bloqueo por IP y por usuario de forma independiente y retorna el máximo de ambos.
     * Devuelve 0 si no hay bloqueo activo o si las consultas retornan resultados vacíos.
     *
     * @param string $ip      Dirección IP del cliente
     * @param string $usuario Nombre de usuario (si vacío, solo se evalúa el bloqueo por IP)
     * @param mysqli $conn    Conexión activa a la base de datos
     * @return int Segundos restantes del bloqueo activo; 0 si no hay bloqueo o ya expiró
     */
    public static function obtenerSegundosBloqueo(string $ip, string $usuario, $conn): int
    {
        $segundos = 0;
        $ventana = self::VENTANA_MINUTOS;

        // Revisar bloqueo por IP
        $sqlIp = "SELECT TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(fecha_intento, INTERVAL {$ventana} MINUTE)) 
                  FROM login_intentos 
                  WHERE ip = ? AND exitoso = 0 AND fecha_intento >= DATE_SUB(NOW(), INTERVAL {$ventana} MINUTE)
                  ORDER BY fecha_intento ASC";
        $stmtIp = $conn->prepare($sqlIp);
        if ($stmtIp) {
            $stmtIp->bind_param("s", $ip);
            $stmtIp->execute();
            $resIp = $stmtIp->get_result();
            if ($resIp->num_rows >= self::MAX_INTENTOS_IP) {
                $offset = $resIp->num_rows - self::MAX_INTENTOS_IP;
                $rows = $resIp->fetch_all(MYSQLI_NUM);
                $segundos = max($segundos, (int) $rows[$offset][0]);
            }
            $stmtIp->close();
        }

        // Revisar bloqueo por Usuario
        if (!empty($usuario)) {
            $sqlUsr = "SELECT TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(fecha_intento, INTERVAL {$ventana} MINUTE)) 
                       FROM login_intentos 
                       WHERE usuario = ? AND exitoso = 0 AND fecha_intento >= DATE_SUB(NOW(), INTERVAL {$ventana} MINUTE)
                       ORDER BY fecha_intento ASC";
            $stmtUsr = $conn->prepare($sqlUsr);
            if ($stmtUsr) {
                $stmtUsr->bind_param("s", $usuario);
                $stmtUsr->execute();
                $resUsr = $stmtUsr->get_result();
                if ($resUsr->num_rows >= self::MAX_INTENTOS_USUARIO) {
                    $offset = $resUsr->num_rows - self::MAX_INTENTOS_USUARIO;
                    $rows = $resUsr->fetch_all(MYSQLI_NUM);
                    $segundos = max($segundos, (int) $rows[$offset][0]);
                }
                $stmtUsr->close();
            }
        }

        return max(0, $segundos);
    }

    // =========================================================
    // TIMEOUT DE SESIÓN (A07 - Broken Authentication)
    // =========================================================

    /**
     * Verifica si la sesión del usuario autenticado ha expirado por inactividad (30 minutos).
     * Si el tiempo transcurrido desde $_SESSION['ultima_actividad'] supera el límite:
     *   1. Llama a session_unset() y session_destroy() para invalidar completamente la sesión
     *   2. Llama a session_start() nuevamente para poder escribir el mensaje flash en $_SESSION
     *   3. Establece un mensaje de error informativo y redirige al login con header()+exit
     * Si la sesión es válida, actualiza $_SESSION['ultima_actividad'] al timestamp actual.
     * Debe llamarse en el bootstrap (public/index.php) antes del enrutamiento principal
     * para que aplique a todas las rutas protegidas sin necesidad de lógica adicional.
     *
     * @return void No retorna valor; si la sesión expiró termina con header(Location)+exit
     */
    public static function validarTimeoutSesion(): void
    {
        // Tiempo máximo de inactividad permitido (30 min)
        $timeout_minutos = 30;
        $timeout_segundos = $timeout_minutos * 60;

        if (isset($_SESSION['usuario_id'])) {
            if (isset($_SESSION['ultima_actividad']) && (time() - $_SESSION['ultima_actividad'] > $timeout_segundos)) {
                // La sesión expiró
                session_unset();
                session_destroy();
                session_start();
                $_SESSION['mensaje_error'] = "Por tu seguridad, tu sesión ha expirado tras $timeout_minutos minutos de inactividad. Inicia sesión nuevamente.";
                $base_url = defined('BASE_URL') ? BASE_URL : '/Ecommerce-Tinkuy/public/index.php';
                header("Location: {$base_url}?page=login");
                exit;
            }
            // Actualiza la marca de tiempo a cada interacción del usuario
            $_SESSION['ultima_actividad'] = time();
        }
    }

    // =========================================================
    // RBAC — Verificación de roles
    // =========================================================

    /**
     * Verifica que el usuario autenticado tenga uno de los roles permitidos.
     * Si no hay sesión activa, redirige a $redirect_page (por defecto: 'login').
     * Si el rol no está en $roles_permitidos, establece un mensaje de error en sesión
     * y redirige a 'index' sin destruir la sesión (solo deniega el acceso a esa ruta).
     * La comparación de roles es case-insensitive: ambos lados se normalizan a minúsculas.
     * Proporciona RBAC más granular que el chequeo binario en controladores individuales.
     *
     * @param array<string> $roles_permitidos Roles válidos, ej. ['admin'] o ['admin','vendedor']
     * @param string        $redirect_page    Parámetro 'page=' para redirigir si no hay sesión activa
     * @return void No retorna valor; en caso de fallo hace exit() después del header(Location)
     */
    public static function verificarRol(array $roles_permitidos, string $redirect_page = 'login'): void
    {
        $base_url = defined('BASE_URL') ? BASE_URL : '/index.php';

        if (!isset($_SESSION['usuario_id'])) {
            header("Location: {$base_url}?page={$redirect_page}");
            exit;
        }

        $rol_actual = strtolower($_SESSION['rol'] ?? '');
        $permitidos = array_map('strtolower', $roles_permitidos);

        if (!in_array($rol_actual, $permitidos, true)) {
            // En lugar de destruir sesión, enviamos un mensaje de error y redirigimos al index
            $_SESSION['mensaje_error'] = "Acceso denegado: No tienes permisos para acceder a esta sección.";
            header("Location: {$base_url}?page=index");
            exit;
        }
    }

    // =========================================================
    // HARDENING DE SESIÓN
    // =========================================================

    /**
     * Configura las opciones de seguridad para la cookie de sesión de PHP.
     * Establece SameSite=Strict (mitigación CSRF), HttpOnly=true (mitigación XSS desde JS),
     * lifetime=0 (la cookie expira al cerrar el navegador) y Secure=true solo en HTTPS
     * para no romper entornos de desarrollo local que no usan HTTPS.
     * También refuerza la configuración a nivel de php.ini en tiempo de ejecución:
     * use_strict_mode=1 para rechazo de IDs de sesión no iniciados por el servidor,
     * use_only_cookies=1 para prevenir session fixation via URL, y cookie_samesite=Strict.
     * IMPORTANTE: Debe llamarse ANTES de session_start() para que PHP aplique
     * los parámetros al generar la cookie de sesión en la respuesta HTTP.
     *
     * @return void
     */
    public static function configurarSesionSegura(): void
    {
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');
        if ($secure) {
            ini_set('session.cookie_secure', '1');
        }
    }

    // =========================================================
    // reCAPTCHA
    // =========================================================

    /**
     * Verifica el token g-recaptcha-response de Google reCAPTCHA v2 mediante una solicitud
     * POST cURL al endpoint https://www.google.com/recaptcha/api/siteverify.
     * Si cURL no puede conectarse o devuelve respuesta vacía, retorna false de forma segura
     * sin lanzar excepción para no interrumpir el flujo de login.
     * La respuesta JSON se decodifica con json_decode() y se verifica el campo 'success';
     * cualquier valor null, vacío o false en 'success' resulta en retorno false.
     * Incluye la IP del cliente en la petición para mejorar el análisis de riesgo de Google.
     * ADVERTENCIA: La clave de prueba '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe'
     * funciona únicamente en localhost; reemplazarla con la clave real antes de producción.
     *
     * @param string $response Token 'g-recaptcha-response' enviado desde el formulario POST
     * @param string $secret   Clave secreta del sitio en Google reCAPTCHA Admin Console
     * @return bool true si Google confirma que el usuario es humano; false en cualquier otro caso
     */
    public static function verificarRecaptcha(string $response, string $secret): bool
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://www.google.com/recaptcha/api/siteverify");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'secret' => $secret,
            'response' => $response,
            'remoteip' => self::obtenerIP()
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $res = curl_exec($ch);
        curl_close($ch);

        if (!$res)
            return false;
        $data = json_decode($res);
        return $data !== null && !empty($data->success);
    }
}
