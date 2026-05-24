<?php
/**
 * Clase centralizada de seguridad para Ecommerce-Tinkuy.
 * Cubre: CSRF, rate limiting (fuerza bruta), RBAC y hardening de sesión.
 * Requiere que la tabla login_intentos exista (database/create_login_intentos.sql).
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

    /** Devuelve (y genera si no existe) el token CSRF de la sesión actual. */
    public static function generarCSRF(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** Valida el token CSRF enviado en el formulario. */
    public static function verificarCSRF(string $token): bool
    {
        return isset($_SESSION['csrf_token'])
            && is_string($token)
            && strlen($token) > 0
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    /** Rota el token CSRF luego de un login exitoso para evitar reutilización. */
    public static function rotarCSRF(): void
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // =========================================================
    // IP
    // =========================================================

    /** Devuelve la IP real del cliente, validada. */
    public static function obtenerIP(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    // =========================================================
    // RATE LIMITING / FUERZA BRUTA
    // =========================================================

    /**
     * Devuelve true si la IP o el usuario han superado el límite de intentos
     * fallidos en la ventana de tiempo configurada.
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

    /** Registra un intento de login (exitoso o fallido) en la base de datos. */
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
     * Limpia los intentos fallidos de un usuario específico una vez que logra ingresar.
     * Esto reinicia su contador a 0 y también descuenta esos fallos del límite global de su IP.
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
     * Devuelve cuántos intentos le quedan a la IP antes de ser bloqueada.
     * Útil para mostrar advertencias al usuario.
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
     * Calcula los segundos restantes de bloqueo para una IP o usuario.
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
     * Valida si la sesión ha expirado por inactividad.
     * Cierra la sesión automáticamente después del tiempo configurado.
     */
    public static function validarTimeoutSesion(): void
    {
        $timeout_minutos = 30; // Tiempo máximo de inactividad permitido (30 min)
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
     * Verifica que el usuario tenga uno de los roles permitidos.
     * Si no está autenticado o el rol no coincide, destruye la sesión y redirige.
     *
     * @param array  $roles_permitidos  Ejemplo: ['admin'] o ['admin','vendedor']
     * @param string $redirect_page     Página a la que redirigir (parámetro `page=`)
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
     * Configura las opciones de seguridad de la cookie de sesión.
     * Debe llamarse ANTES de session_start().
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
     * Verifica el token de Google reCAPTCHA v2 usando cURL
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
        $res = curl_exec($ch);
        curl_close($ch);

        if (!$res)
            return false;
        $data = json_decode($res);
        return $data !== null && !empty($data->success);
    }
}
